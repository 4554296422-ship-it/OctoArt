(function () {
  const estados = [
    ['AC','Acre'],['AL','Alagoas'],['AP','Amapá'],['AM','Amazonas'],
    ['BA','Bahia'],['CE','Ceará'],['DF','Distrito Federal'],['ES','Espírito Santo'],
    ['GO','Goiás'],['MA','Maranhão'],['MT','Mato Grosso'],['MS','Mato Grosso do Sul'],
    ['MG','Minas Gerais'],['PA','Pará'],['PB','Paraíba'],['PR','Paraná'],
    ['PE','Pernambuco'],['PI','Piauí'],['RJ','Rio de Janeiro'],['RN','Rio Grande do Norte'],
    ['RS','Rio Grande do Sul'],['RO','Rondônia'],['RR','Roraima'],['SC','Santa Catarina'],
    ['SP','São Paulo'],['SE','Sergipe'],['TO','Tocantins']
  ];
  const IBGE = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados/';
  const cache = {};

  function isDarkMode() {
    return document.documentElement.classList.contains('dark');
  }

  function normalize(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR');
  }

  function bindCityInput(input) {
    if (!input || input.dataset.cityBound) return;
    input.dataset.cityBound = '1';

    const wrap = document.createElement('div');
    wrap.className = 'field-suggest';
    wrap.style.position = 'relative';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    const box = document.createElement('div');
    box.className = 'city-suggest';
    Object.assign(box.style, {
      display: 'none', position: 'absolute', zIndex: '9999', top: '100%', left: '0',
      right: '0', maxHeight: '260px', overflowY: 'auto',
      background: isDarkMode() ? '#161322' : '#FFFFFF',
      color: isDarkMode() ? '#F2F1F8' : '#242136',
      border: isDarkMode() ? '1px solid #3A3558' : '1px solid #D8DCE5', borderRadius: '10px',
      boxShadow: '0 8px 24px rgba(0,0,0,.28)', padding: '5px'
    });
    wrap.appendChild(box);

    let uf = '';
    let cidades = [];
    let loading = false;

    function hide() {
      box.style.display = 'none';
      box.innerHTML = '';
    }

    function addButton(label, onClick, note) {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = label;
      Object.assign(button.style, {
        display: 'block', width: '100%', textAlign: 'left', border: '0',
        borderRadius: '7px', padding: '10px 12px', margin: '1px 0',
        background: 'transparent', color: isDarkMode() ? '#F2F1F8' : '#242136', cursor: 'pointer',
        font: 'inherit'
      });
      button.addEventListener('mouseenter', () => {
        button.style.background = isDarkMode() ? '#292344' : '#F0EDFF';
        button.style.color = isDarkMode() ? '#FFFFFF' : '#24165C';
      });
      button.addEventListener('mouseleave', () => button.style.background = 'transparent');
      if (note) {
        const small = document.createElement('small');
        small.textContent = note;
        small.style.opacity = '.7';
        small.style.marginLeft = '6px';
        button.appendChild(small);
      }
      button.addEventListener('click', onClick);
      box.appendChild(button);
    }

    function showStates(filter) {
      box.innerHTML = '';
      const query = normalize(filter);
      const matches = estados.filter(([sigla, nome]) =>
        !query || normalize(nome).includes(query) || normalize(sigla).includes(query)
      );
      if (!matches.length) {
        addButton('Nenhum estado encontrado', () => {});
      } else {
        matches.forEach(([sigla, nome]) => addButton(`${nome} (${sigla})`, () => loadCities(sigla, nome)));
      }
      box.style.display = 'block';
    }

    async function loadCities(sigla, nome) {
      uf = sigla;
      input.value = '';
      input.placeholder = `Busque uma cidade de ${nome}`;
      box.innerHTML = '';
      addButton('Carregando cidades…', () => {});
      box.style.display = 'block';
      try {
        if (!cache[sigla]) {
          const response = await fetch(`${IBGE}${sigla}/municipios?orderBy=nome`);
          if (!response.ok) throw new Error('Não foi possível carregar as cidades');
          cache[sigla] = (await response.json()).map(item => item.nome);
        }
        cidades = cache[sigla];
        renderCities('');
        input.focus();
      } catch (error) {
        box.innerHTML = '';
        addButton('Não foi possível carregar a lista. Clique para tentar novamente.', () => loadCities(sigla, nome));
      }
    }

    function renderCities(filter) {
      box.innerHTML = '';
      const query = normalize(filter);
      const matches = cidades.filter(nome => normalize(nome).includes(query));
      if (!matches.length) addButton('Nenhuma cidade encontrada', () => {});
      else matches.slice(0, 100).forEach(nome => addButton(`${nome}, ${uf}`, () => {
        input.value = `${nome}, ${uf}`;
        input.placeholder = 'Selecione sua cidade';
        hide();
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }));
      if (matches.length > 100) {
        const info = document.createElement('div');
        info.textContent = `Digite mais letras para filtrar (${matches.length} resultados).`;
        Object.assign(info.style, { padding: '8px 12px', opacity: '.7', fontSize: '.85em' });
        box.appendChild(info);
      }
      box.style.display = 'block';
    }

    input.setAttribute('autocomplete', 'off');
    input.setAttribute('aria-haspopup', 'listbox');
    input.placeholder = input.value || 'Selecione o estado e depois a cidade';

    input.addEventListener('focus', () => {
      if (uf && cidades.length) renderCities(input.value);
      else showStates('');
    });

    input.addEventListener('input', () => {
      if (uf && cidades.length) renderCities(input.value);
      else showStates(input.value);
    });

    input.addEventListener('keydown', event => {
      if (event.key === 'Escape') hide();
      if (event.key === 'ArrowDown' && box.style.display === 'none') showStates('');
    });

    document.addEventListener('click', event => {
      if (!wrap.contains(event.target)) hide();
    });
  }

  document.querySelectorAll('input[data-city], input[name="cidade"], #cidadeInput').forEach(bindCityInput);
  window.bindCityInputs = function (root) {
    (root || document).querySelectorAll('input[data-city], input[name="cidade"], #cidadeInput').forEach(bindCityInput);
  };
})();
