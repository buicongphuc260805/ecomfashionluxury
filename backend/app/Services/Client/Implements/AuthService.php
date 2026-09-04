<?php

namespace App\Services\Client\Implements;

use App\Mail\OtpMail;
use App\Models\Customer;
use App\Repositories\Client\Interfaces\AuthRepositoryInterface;
use App\Services\Client\Interfaces\AuthServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class AuthService implements AuthServiceInterface
{
    public function __construct(private readonly AuthRepositoryInterface $repo) {}

    public function register(array $data): array
    {
        $customer = $this->repo->createCustomer([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
            'password' => $data['password'],
            'status' => 1,
        ]);

        $token = $customer->createToken('customer_token')->plainTextToken;

        return [
            'success' => true,
            'message' => 'Customer registered successfully',
            'token' => $token,
            'user' => $customer,
        ];
    }

    public function login(array $data): array
    {
        $customer = $this->repo->findByEmail($data['email']);

        if (! $customer || ! Hash::check($data['password'], $customer->password)) {
            return [
                'success' => false,
                'message' => 'Email hoặc mật khẩu không chính xác.',
            ];
        }

        if ($customer->status !== 1) {
            return [
                'success' => false,
                'message' => 'Tài khoản của bạn đã bị khóa.',
            ];
        }

        $token = $customer->createToken('customer_token')->plainTextToken;

        return [
            'success' => true,
            'message' => 'Customer logged in successfully',
            'token' => $token,
            'user' => $customer,
        ];
    }

    public function logout(Customer $customer): void
    {
        /** @var PersonalAccessToken|null $token */
        $token = $customer->currentAccessToken();

        $token?->delete();
    }

    public function forgotPassword(string $email): array
    {

        $otp = sprintf('%06d', mt_rand(1, 999999));

        try {
            $this->repo->clearExistingOtps($email);
            $this->repo->createOtpRecord($email, $otp);

            try {
                Mail::to($email)->send(new OtpMail($otp));
            } catch (Throwable $mailEx) {
                // Render Free Tier chặn các cổng SMTP 25, 465, 587.
                // Tự động dùng Brevo HTTP API (Port 443 HTTPS) - không bao giờ bị chặn trên Cloud!
                $brevoApiKey = env('BREVO_API_KEY') ?: env('MAIL_PASSWORD');
                $senderEmail = env('MAIL_FROM_ADDRESS', 'phuc0862605045@gmail.com');
                $senderName = env('MAIL_FROM_NAME', 'Ecom Fashion Luxury');

                if ($brevoApiKey) {
                    $response = Http::withHeaders([
                        'api-key' => $brevoApiKey,
                        'accept' => 'application/json',
                        'content-type' => 'application/json',
                    ])->post('https://api.brevo.com/v3/smtp/email', [
                        'sender' => ['name' => $senderName, 'email' => $senderEmail],
                        'to' => [['email' => $email]],
                        'subject' => 'Mã xác thực OTP đặt lại mật khẩu - Ecom Fashion',
                        'htmlContent' => "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee;'>
                                <h2 style='color: #333; text-align: center; font-weight: bold;'>ECOM FASHION</h2>
                                <p>Xin chào,</p>
                                <p>Chúng tôi đã nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Vui lòng sử dụng mã OTP dưới đây để tiếp tục:</p>
                                <div style='text-align: center; margin: 30px 0;'>
                                    <span style='font-size: 24px; font-weight: bold; letter-spacing: 5px; padding: 10px 20px; background-color: #f5f5f5; border: 1px solid #ddd; border-radius: 4px;'>{$otp}</span>
                                </div>
                                <p style='color: #666; font-size: 13px;'>Mã xác thực này có hiệu lực trong vòng 10 phút. Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.</p>
                            </div>
                        ",
                    ]);

                    if ($response->successful()) {
                        return [
                            'success' => true,
                            'message' => 'Mã OTP đã được gửi về email của bạn.',
                        ];
                    }

                    throw new \Exception('Brevo API error: '.$response->body());
                }

                throw $mailEx;
            }

            return [
                'success' => true,
                'message' => 'Mã OTP đã được gửi về email của bạn.',
            ];
        } catch (Throwable $e) {
            Log::error('[forgotPassword] '.$e->getMessage());

            return [
                'success' => false,
                'message' => config('app.debug') ? '[Mail Error] '.$e->getMessage() : 'Đã xảy ra lỗi hệ thống, vui lòng thử lại sau.',
            ];
        }
    }

    public function verifyOtp(string $email, string $otpCode): array
    {
        $otpRecord = $this->repo->findOtpRecord($email, $otpCode);

        if (! $otpRecord) {
            return [
                'success' => false,
                'message' => 'Mã OTP không hợp lệ hoặc đã hết hạn.',
            ];
        }

        $this->repo->markOtpAsUsed($otpRecord->id);

        $resetToken = Str::random(64);
        Cache::put('reset_token_'.$resetToken, $email, now()->addMinutes(10));

        return [
            'success' => true,
            'reset_token' => $resetToken,
        ];
    }

    public function resetPassword(string $token, string $password): array
    {
        $email = Cache::get('reset_token_'.$token);

        if (! $email) {
            return [
                'success' => false,
                'message' => 'Token xác thực đã hết hạn hoặc không hợp lệ.',
            ];
        }

        $customer = $this->repo->findByEmail($email);
        if (! $customer) {
            return [
                'success' => false,
                'message' => 'Không tìm thấy thông tin khách hàng.',
            ];
        }

        $this->repo->updateCustomer($customer, ['password' => $password]);

        Cache::forget('reset_token_'.$token);

        return [
            'success' => true,
            'message' => 'Mật khẩu của bạn đã được cập nhật thành công.',
        ];
    }

    public function updateProfile(Customer $customer, array $data): array
    {
        $this->repo->updateCustomer($customer, [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone_number' => $data['phone_number'] ?? null,
        ]);

        return [
            'success' => true,
            'message' => 'Cập nhật thông tin thành công.',
            'data' => $customer,
        ];
    }

    public function changePassword(Customer $customer, array $data): array
    {
        if (! Hash::check($data['current_password'], $customer->password)) {
            return [
                'success' => false,
                'message' => 'Mật khẩu hiện tại không chính xác.',
            ];
        }

        $this->repo->updateCustomer($customer, ['password' => $data['new_password']]);

        return [
            'success' => true,
            'message' => 'Đổi mật khẩu thành công.',
        ];
    }
}
