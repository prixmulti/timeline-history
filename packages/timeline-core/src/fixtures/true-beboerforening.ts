/**
 * Uddrag fra True Beboerforenings offentlige tidslinie (50 år).
 * Kilde: https://www.true-beboerforening.dk/tidslinie/
 */
import type { TimelinePayload } from '../types';
import { defaultConfig } from '../types';

const base = 'https://www.true-beboerforening.dk';

export const trueBeboerforeningPayload: TimelinePayload = {
  config: {
    ...defaultConfig(),
    layout: 'vertical',
    theme: 'editorial',
    skin: 'light',
    animation: 'fade-in',
    dateFormat: 'yyyy',
    showMedia: true,
    maxStories: 0,
    tokens: {
      colors: {
        line: '#2a5c45',
        accent: '#c45a28',
        cardBg: '#faf8f4',
      },
    },
  },
  stories: [
    {
      id: 'true-1974',
      date: '1974-03-01',
      title: 'True Beboerforening stiftes',
      excerpt:
        'Foreningen stiftes med det formål at varetage beboernes interesser over for myndigheder og skabe sammenhold i byen.',
      images: [`${base}/media/2024/01/artikel_stiftelse-1536x1158.jpg`],
      accentColor: '#2a5c45',
    },
    {
      id: 'true-1976',
      date: '1976-08-01',
      title: 'Lukning af True Skole',
      excerpt:
        'True Skole med 1.–7. klasse lukkes; omkring 100 elever overføres til den nybyggede Skjoldhøjskole.',
      images: [`${base}/media/2024/01/true-skole-1030x529.jpg`],
    },
    {
      id: 'true-1978',
      date: '1978-04-01',
      title: 'Åbning af True Fritidshjem',
      excerpt:
        'Fritidshjemmet åbner 1. april i den nedlagte skole – plads til 40 børn fra True og omegn.',
      images: [`${base}/media/2024/01/historie18-1030x687.jpg`],
    },
    {
      id: 'true-1981',
      date: '1981-01-01',
      title: 'Den nye Anelystvej',
      excerpt:
        'Trafikken fra Tilst mod Brabrand føres uden om landsbyen, da Anelystvej etableres – en markant ændring for hverdagen i True.',
      images: [`${base}/media/2024/01/anelystvej-1030x926.jpg`],
    },
    {
      id: 'true-1988',
      date: '1988-01-01',
      title: 'Købmand Krogstrup lukker',
      excerpt:
        'William og Hilda Krogstrup driver butikken fra 1946 til 1988. Den har været samlingssted for landsbyen i alle årene.',
    },
    {
      id: 'true-1994',
      date: '1994-01-01',
      title: 'True Skov',
      excerpt:
        'De første træer plantes ved Skjoldhøjskolen – starten på True Skov som del af ideen om Aarhus omkranset af skov.',
      images: [`${base}/media/2024/01/ridebane-1030x576.jpg`],
    },
    {
      id: 'true-2003',
      date: '2003-05-01',
      title: 'True Tingsted indvies',
      excerpt:
        '1. maj overtager beboerforeningen Tingstedet officielt; indvielsesstenen af Hans Hansen, Tingvad, afsløres.',
      images: [`${base}/media/2024/01/historie20-1030x687.jpg`],
    },
    {
      id: 'true-2014',
      date: '2014-09-07',
      title: '40 års jubilæum',
      excerpt:
        'Jubilæumsdagen markeres med "Tour de True" – beboerne bevæger sig rundt til poster i landsbyen.',
      icon: '★',
      images: [
        `${base}/media/2024/01/82-sommer11-1030x687.jpg`,
        `${base}/media/2024/01/82-sommer06-1030x687.jpg`,
        `${base}/media/2024/01/82-sommer13-1030x687.jpg`,
        `${base}/media/2024/01/82-sommer07-1030x687.jpg`,
      ],
    },
    {
      id: 'true-2017',
      date: '2017-01-01',
      title: 'Gamle skole på private hænder',
      excerpt:
        'Aarhus Kommune sælger True gamle skole og gymnastiksalen; bygningerne gennemgår større renovering.',
    },
    {
      id: 'true-2018',
      date: '2018-01-01',
      title: 'Ny hjemmeside',
      excerpt:
        'Hjemmesiden true-beboerforening.dk opgraderes i WordPress – med bedre muligheder for at formidle foreningens historie.',
      images: [`${base}/media/social/og-share.jpg`],
    },
    {
      id: 'true-2022',
      date: '2022-01-01',
      title: 'Vild med True',
      excerpt:
        'Beboerforeningen etablerer bede med vilde blomster på Tingstedet – fokus på biodiversitet i landsbyen.',
      images: [`${base}/media/2024/01/6fa1622b380445ed88c3532e5c584e2e-1030x708.jpg`],
    },
    {
      id: 'true-2024',
      date: '2024-01-01',
      title: '50 års jubilæum',
      excerpt:
        'Tidslinien "50 år" samler landsbyens historie fra 1974 og frem – foreningen holder fortsat arrangementer i True.',
      icon: '★',
      accentColor: '#c45a28',
    },
  ],
};
