import type { ComponentProps, Ref } from 'react';
import { Link } from '@inertiajs/react';

type RouterLinkProps = ComponentProps<typeof Link> & {
    ref?: Ref<HTMLAnchorElement>;
};

export function RouterLink({ ref, ...props }: RouterLinkProps) {
    return <Link ref={ref} {...props} />;
}
