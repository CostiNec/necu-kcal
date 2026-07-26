import type { PropsWithChildren } from 'react';
import { motion, useReducedMotion } from 'framer-motion';

export function PageTransition({ children }: PropsWithChildren) {
    const reducedMotion = useReducedMotion();

    return (
        <motion.div
            initial={
                reducedMotion
                    ? false
                    : { opacity: 0, y: 18, scale: 0.995 }
            }
            animate={{ opacity: 1, y: 0, scale: 1 }}
            transition={{
                type: 'spring',
                stiffness: 260,
                damping: 26,
                mass: 0.7,
            }}
        >
            {children}
        </motion.div>
    );
}
