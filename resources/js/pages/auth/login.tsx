import { Form, Head, Link } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
/* @chisel-passkeys */
import PasskeyVerify from '@/components/passkey-verify';
/* @end-chisel-passkeys */

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Log in" />

            <main className="min-h-svh bg-[#202020] p-0 text-[#101820] md:p-5">
                <div className="grid min-h-svh overflow-hidden bg-[#dedede] md:min-h-[calc(100svh-2.5rem)] md:grid-cols-[0.95fr_1.05fr]">
                    <section className="flex min-h-[390px] flex-col justify-between bg-[#05051f] px-7 py-8 text-white sm:px-10 sm:py-9 lg:px-12 lg:py-10">
                        <Link
                            href={home()}
                            className="w-fit leading-none tracking-[-0.035em] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-yellow-300"
                            aria-label="TOMECO CrossGuard home"
                        >
                            <span className="block text-[1.35rem] font-bold sm:text-2xl">
                                TOMECO
                            </span>
                            <span className="mt-1 block text-[1.35rem] font-bold sm:text-2xl">
                                <span className="text-yellow-400">Cross</span>{' '}
                                <span>Guard</span>
                            </span>
                        </Link>

                        <div className="my-12 max-w-xl sm:my-14 md:my-auto md:py-16">
                            <h1 className="text-3xl font-bold leading-tight tracking-[-0.035em] sm:text-4xl lg:text-[2.65rem]">
                                Safer crossings.
                                <br />
                                Smarter enforcement.
                            </h1>
                            <p className="mt-8 max-w-lg text-sm leading-relaxed text-white/90 sm:mt-10 sm:text-base">
                                Real-time pedestrian crossing monitoring and
                                evidence-based incident management for
                                authorized TOMECO personnel.
                            </p>
                        </div>

                        <div className="flex w-fit items-center gap-3 border-l-4 border-sky-600 bg-white/15 px-3 py-2.5 pr-5">
                            <MapPin
                                aria-hidden="true"
                                className="size-7 shrink-0 text-white"
                                strokeWidth={1.7}
                            />
                            <div>
                                <p className="text-[0.6rem] font-medium uppercase tracking-[0.14em] text-white/65">
                                    Designated monitoring site
                                </p>
                                <p className="mt-0.5 text-xs font-semibold text-white sm:text-sm">
                                    LNU Youngfield Pedestrian Crossing
                                </p>
                            </div>
                        </div>
                    </section>

                    <section className="flex min-h-[600px] items-center justify-center px-6 py-14 sm:px-10 lg:px-14 xl:px-20">
                        <div className="w-full max-w-[470px]">
                            {/* @chisel-passkeys */}
                            <PasskeyVerify />
                            {/* @end-chisel-passkeys */}

                            <header className="mb-8 sm:mb-10">
                                <h2 className="text-3xl font-bold tracking-[-0.035em] text-[#4a4a4a] sm:text-4xl">
                                    Welcome back
                                </h2>
                                <p className="mt-1 text-sm text-[#5b5b5b]">
                                    Sign in to access CrossGuard operations
                                    dashboard.
                                </p>
                            </header>

                            <Form
                                {...store.form()}
                                resetOnSuccess={['password']}
                                className="flex flex-col gap-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-5">
                                            <div className="grid gap-2">
                                                <Label
                                                    htmlFor="email"
                                                    className="text-xs font-medium text-[#404040]"
                                                >
                                                    Email address
                                                </Label>
                                                <Input
                                                    id="email"
                                                    type="email"
                                                    name="email"
                                                    required
                                                    autoFocus
                                                    tabIndex={1}
                                                    autoComplete="email"
                                                    placeholder="email@example.com"
                                                    className="h-11 border-[#d0d0d0] bg-white text-sm text-[#161616] shadow-sm placeholder:text-[#777] focus-visible:ring-[#002b49]"
                                                />
                                                <InputError
                                                    message={errors.email}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <div className="flex items-center justify-between">
                                                    <Label
                                                        htmlFor="password"
                                                        className="text-xs font-medium text-[#404040]"
                                                    >
                                                        Password
                                                    </Label>
                                                    {canResetPassword && (
                                                        <TextLink
                                                            href={request()}
                                                            className="text-xs text-sky-700 no-underline hover:text-sky-900 hover:underline"
                                                            tabIndex={5}
                                                        >
                                                            Forgot password?
                                                        </TextLink>
                                                    )}
                                                </div>
                                                <PasswordInput
                                                    id="password"
                                                    name="password"
                                                    required
                                                    tabIndex={2}
                                                    autoComplete="current-password"
                                                    placeholder="Enter password"
                                                    className="h-11 border-[#d0d0d0] bg-white text-sm text-[#161616] shadow-sm placeholder:text-[#777] focus-visible:ring-[#002b49]"
                                                />
                                                <InputError
                                                    message={errors.password}
                                                />
                                            </div>

                                            <div className="flex items-center gap-2">
                                                <Checkbox
                                                    id="remember"
                                                    name="remember"
                                                    tabIndex={3}
                                                    className="border-[#8a8a8a] data-[state=checked]:border-[#002b49] data-[state=checked]:bg-[#002b49]"
                                                />
                                                <Label
                                                    htmlFor="remember"
                                                    className="text-xs font-normal text-[#555]"
                                                >
                                                    Remember me
                                                </Label>
                                            </div>
                                        </div>

                                        <div>
                                            <Button
                                                type="submit"
                                                className="h-11 w-full rounded-md bg-[#00253e] font-semibold text-white hover:bg-[#003653]"
                                                tabIndex={4}
                                                disabled={processing}
                                                data-test="login-button"
                                            >
                                                {processing && <Spinner />}
                                                Sign In
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>

                            {status && (
                                <div className="mt-5 text-sm font-medium text-green-700">
                                    {status}
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}

function LoginLayout({ children }: { children: ReactNode }) {
    return <>{children}</>;
}

Login.layout = LoginLayout;
