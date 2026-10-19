<script>
    (() => {
        const fits = (label) => {
            const content = label.querySelector('.content');
            const information = label.querySelector('.information');
            const tolerance = 1;

            return label.scrollHeight <= label.clientHeight + tolerance
                && content.scrollHeight <= content.clientHeight + tolerance
                && information.scrollHeight <= information.clientHeight + tolerance;
        };

        const fitLabels = () => {
            document.querySelectorAll('.asset-label').forEach((label) => {
                label.style.setProperty('--text-fit', '1');

                if (fits(label)) {
                    return;
                }

                let smallest = 0.55;
                let largest = 1;
                label.style.setProperty('--text-fit', String(smallest));

                if (!fits(label)) {
                    return;
                }

                for (let step = 0; step < 10; step++) {
                    const middle = (smallest + largest) / 2;
                    label.style.setProperty('--text-fit', String(middle));

                    if (fits(label)) {
                        smallest = middle;
                    } else {
                        largest = middle;
                    }
                }

                label.style.setProperty('--text-fit', String(smallest));
            });
        };

        fitLabels();
        window.addEventListener('beforeprint', fitLabels);
    })();
</script>
