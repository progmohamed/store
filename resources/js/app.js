const medicineName = document.getElementById('medicine-name');
if (medicineName) {
    const options = [...document.querySelectorAll('#medicine-options option')];
    const hint = document.getElementById('medicine-hint');
    const minimum = document.getElementById('new-medicine-minimum');
    const update = () => {
        const name = medicineName.value.trim().replace(/\s+/g, ' ');
        const existing = options.find(option => option.value === name);
        minimum.hidden = Boolean(existing);
        minimum.querySelector('input').disabled = Boolean(existing);
        hint.textContent = existing
            ? `دواء موجود · الرصيد الحالي ${existing.dataset.stock} وحدة. هيتضاف الوارد لنفس الدواء.`
            : name ? 'دواء جديد · هيتضاف الاسم والوارد معًا عند التسجيل.'
                : 'اختار من الأسماء الموجودة أو اكتب اسم دواء جديد.';
    };
    medicineName.addEventListener('input', update);
    update();
}
