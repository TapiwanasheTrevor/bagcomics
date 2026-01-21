
import { Comic } from './types';

// Real comic uploaded to Cloudinary
export const MOCK_COMICS: Comic[] = [
  {
    id: '103',
    slug: 'the-anointor-book-one',
    title: 'The Anointor: Book One',
    author: 'Bag Concepts',
    description: 'The first chapter of The Anointor saga - an epic African fantasy adventure.',
    coverImage: 'https://res.cloudinary.com/dbhxdtsxo/image/upload/v1768458871/bagcomics/covers/the-anointor-book-one/cover.jpg',
    genre: ['Fantasy', 'Action'],
    rating: 5,
    totalChapters: 15,
    episodes: 1,
    likesCount: 0,
    commentsCount: 0,
    isLiked: false,
    isBookmarked: false,
    isFree: true,
    pages: [
      'https://res.cloudinary.com/dbhxdtsxo/image/upload/v1768458876/bagcomics/pages/the-anointor-book-one/page_0001.jpg',
      'https://res.cloudinary.com/dbhxdtsxo/image/upload/v1768458881/bagcomics/pages/the-anointor-book-one/page_0002.png',
      'https://res.cloudinary.com/dbhxdtsxo/image/upload/v1768458885/bagcomics/pages/the-anointor-book-one/page_0003.png',
      'https://res.cloudinary.com/dbhxdtsxo/image/upload/v1768458890/bagcomics/pages/the-anointor-book-one/page_0004.jpg',
      'https://res.cloudinary.com/dbhxdtsxo/image/upload/v1768458896/bagcomics/pages/the-anointor-book-one/page_0005.jpg',
      'https://res.cloudinary.com/dbhxdtsxo/image/upload/v1768458901/bagcomics/pages/the-anointor-book-one/page_0006.jpg',
      'https://res.cloudinary.com/dbhxdtsxo/image/upload/v1768458908/bagcomics/pages/the-anointor-book-one/page_0007.jpg',
      'https://res.cloudinary.com/dbhxdtsxo/image/upload/v1768458913/bagcomics/pages/the-anointor-book-one/page_0008.png',
      'https://res.cloudinary.com/dbhxdtsxo/image/upload/v1768458919/bagcomics/pages/the-anointor-book-one/page_0009.png',
      'https://res.cloudinary.com/dbhxdtsxo/image/upload/v1768458925/bagcomics/pages/the-anointor-book-one/page_0010.jpg',
      'https://res.cloudinary.com/dbhxdtsxo/image/upload/v1768458930/bagcomics/pages/the-anointor-book-one/page_0011.jpg',
      'https://res.cloudinary.com/dbhxdtsxo/image/upload/v1768458935/bagcomics/pages/the-anointor-book-one/page_0012.jpg',
      'https://res.cloudinary.com/dbhxdtsxo/image/upload/v1768458941/bagcomics/pages/the-anointor-book-one/page_0013.jpg',
      'https://res.cloudinary.com/dbhxdtsxo/image/upload/v1768458947/bagcomics/pages/the-anointor-book-one/page_0014.jpg',
      'https://res.cloudinary.com/dbhxdtsxo/image/upload/v1768458952/bagcomics/pages/the-anointor-book-one/page_0015.jpg'
    ]
  },
  {
    id: '2',
    slug: 'eroja-defining-power',
    title: 'EROJA: Defining Power',
    author: 'Taniart Khalz',
    description: 'Epic fantasy following the path of destiny.',
    coverImage: 'https://picsum.photos/seed/ero1/600/900',
    genre: ['Fantasy', 'Action', 'Family'],
    rating: 5,
    totalChapters: 45,
    episodes: 2,
    pages: ['https://picsum.photos/seed/p4/800/1200', 'https://picsum.photos/seed/p5/800/1200']
  },
  {
    id: '3',
    slug: 'okunkun-orun-godfall',
    title: 'OKUNKUN ORUN: Godfall',
    author: 'Afro Padre',
    description: 'When gods fall, mortals must take their place.',
    coverImage: 'https://picsum.photos/seed/oku1/600/900',
    genre: ['Fantasy', 'Supernatural'],
    rating: 5,
    totalChapters: 28,
    episodes: 1,
    pages: ['https://picsum.photos/seed/p6/800/1200']
  },
  {
    id: '4',
    slug: 'ihuhu-vendetta',
    title: 'IHUHU: Vendetta',
    author: 'Taniart Hermann',
    description: 'Crime and mystery in the underworld.',
    coverImage: 'https://picsum.photos/seed/ihu1/600/900',
    genre: ['Action', 'Crime', 'Mystery'],
    rating: 5,
    totalChapters: 15,
    episodes: 1,
    pages: ['https://picsum.photos/seed/p7/800/1200']
  },
  {
    id: '5',
    slug: 'the-guard-origin',
    title: 'The Guard Origin',
    author: 'Saint von Okaba',
    description: 'The story of how the legendary guards were formed.',
    coverImage: 'https://picsum.photos/seed/guard1/600/900',
    genre: ['Action', 'Sci-Fi', 'Fantasy'],
    rating: 5,
    totalChapters: 10,
    episodes: 1,
    pages: ['https://picsum.photos/seed/p8/800/1200']
  },
  {
    id: '6',
    slug: 'zephilia',
    title: 'ZEPHILIA',
    author: 'D.K. Night',
    description: 'A tale of supernatural wonders.',
    coverImage: 'https://picsum.photos/seed/zeph1/600/900',
    genre: ['Action', 'Fantasy'],
    rating: 5,
    totalChapters: 30,
    episodes: 1,
    pages: ['https://picsum.photos/seed/p9/800/1200']
  }
];
