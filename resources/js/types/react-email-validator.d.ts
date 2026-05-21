// `react-email-validator` ships no TypeScript types of its own. The
// runtime export is `validate(email: string): boolean` plus a stale
// shared `res` boolean we don't use.
declare module 'react-email-validator' {
    export function validate(email: string): boolean;
    export const res: boolean;
}
