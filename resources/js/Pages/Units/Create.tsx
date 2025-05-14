const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    router.post('/units', {
        code: formState.code,
        name: formState.name,
        semester_id: formState.semester_id || null, // Ensure semester_id is included
    }, {
        onSuccess: () => {
            toast.success('Unit created successfully!');
        },
        onError: (errors) => {
            console.error('Failed to create unit:', errors);
            toast.error('Failed to create unit.');
        },
    });
};
