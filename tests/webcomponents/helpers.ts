export const svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" style="overflow: visible"><path d="M1 1h22v22H1z"/></svg>';

export const response = () => new Response(JSON.stringify({ svg }), {
	headers: { 'Content-Type': 'application/json' },
});

export function deferred<T>() {
	let resolve!: (value: T) => void;
	let reject!: (reason: unknown) => void;
	const promise = new Promise<T>((yes, no) => { resolve = yes; reject = no; });
	return { promise, resolve, reject };
}

export const tick = () => new Promise<void>((resolve) => setTimeout(resolve, 0));

let sequence = 0;
export const iconName = () => `test:icon-${Date.now()}-${++sequence}`;
