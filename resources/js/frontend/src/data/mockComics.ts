export interface Comic {
  id: string;
  title: string;
  creator: string;
  genre: string[];
  description: string;
  coverImage: string;
  rating: number;
  episodeCount: number;
  price: number;
  isFree: boolean;
  isNew: boolean;
  isTrending: boolean;
  readProgress?: number;
  isBookmarked?: boolean;
  previewPages: string[];
  language: string;
  createdAt: string;
}

export const mockComics: Comic[] = [
  {
    id: '1',
    title: 'Anansi Chronicles',
    creator: 'Kwame Asante',
    genre: ['Fantasy', 'Mythology'],
    description: 'Follow the legendary spider god Anansi as he weaves through modern Ghana, bringing ancient wisdom to contemporary challenges. This epic tale blends traditional folklore with urban fantasy.',
    coverImage: 'https://images.pexels.com/photos/17867069/pexels-photo-17867069.jpeg?auto=compress&cs=tinysrgb&w=400',
    rating: 4.8,
    episodeCount: 12,
    price: 2.99,
    isFree: false,
    isNew: true,
    isTrending: true,
    readProgress: 35,
    isBookmarked: true,
    previewPages: ['page1.jpg', 'page2.jpg', 'page3.jpg'],
    language: 'English',
    createdAt: '2024-01-15'
  },
  {
    id: '2',
    title: 'Lagos 2090',
    creator: 'Aisha Okafor',
    genre: ['Sci-Fi', 'Action'],
    description: 'In a cyberpunk Lagos of the future, a young hacker discovers a conspiracy that threatens the megacity. Neon-lit adventures meet African futurism in this thrilling series.',
    coverImage: 'https://images.pexels.com/photos/17867321/pexels-photo-17867321.jpeg?auto=compress&cs=tinysrgb&w=400',
    rating: 4.6,
    episodeCount: 8,
    price: 3.49,
    isFree: false,
    isNew: false,
    isTrending: true,
    readProgress: 0,
    isBookmarked: false,
    previewPages: ['page1.jpg', 'page2.jpg', 'page3.jpg'],
    language: 'English',
    createdAt: '2023-11-20'
  },
  {
    id: '3',
    title: 'Queen Nzinga: Warrior Spirit',
    creator: 'Mandla Sibeko',
    genre: ['Historical', 'Action'],
    description: 'The untold story of Queen Nzinga of Ndongo and Matamba, one of Africa\'s greatest warrior queens. Experience her fierce battles against Portuguese colonizers.',
    coverImage: 'https://images.pexels.com/photos/16465562/pexels-photo-16465562.jpeg?auto=compress&cs=tinysrgb&w=400',
    rating: 4.9,
    episodeCount: 15,
    price: 0,
    isFree: true,
    isNew: false,
    isTrending: true,
    readProgress: 80,
    isBookmarked: true,
    previewPages: ['page1.jpg', 'page2.jpg', 'page3.jpg'],
    language: 'English',
    createdAt: '2023-09-10'
  },
  {
    id: '4',
    title: 'Orisha Rising',
    creator: 'Folake Adebayo',
    genre: ['Fantasy', 'Adventure'],
    description: 'When the Orishas return to Earth, a young priestess must unite the scattered tribes of West Africa to face an ancient evil. Magic and mythology collide.',
    coverImage: 'https://images.pexels.com/photos/17648436/pexels-photo-17648436.jpeg?auto=compress&cs=tinysrgb&w=400',
    rating: 4.7,
    episodeCount: 10,
    price: 2.49,
    isFree: false,
    isNew: true,
    isTrending: false,
    readProgress: 0,
    isBookmarked: false,
    previewPages: ['page1.jpg', 'page2.jpg', 'page3.jpg'],
    language: 'English',
    createdAt: '2024-01-05'
  },
  {
    id: '5',
    title: 'Ubuntu Squad',
    creator: 'Thabo Mthembu',
    genre: ['Superhero', 'Action'],
    description: 'A team of African superheroes with powers rooted in Ubuntu philosophy must protect Johannesburg from interdimensional threats. Ubuntu means "I am because we are".',
    coverImage: 'https://images.pexels.com/photos/17648447/pexels-photo-17648447.jpeg?auto=compress&cs=tinysrgb&w=400',
    rating: 4.5,
    episodeCount: 20,
    price: 1.99,
    isFree: false,
    isNew: false,
    isTrending: true,
    readProgress: 25,
    isBookmarked: true,
    previewPages: ['page1.jpg', 'page2.jpg', 'page3.jpg'],
    language: 'English',
    createdAt: '2023-08-15'
  },
  {
    id: '6',
    title: 'Sahara Nomads',
    creator: 'Amina Hassan',
    genre: ['Adventure', 'Drama'],
    description: 'Follow a Tuareg family as they navigate the changing Sahara Desert, facing both natural challenges and the encroachment of modern civilization.',
    coverImage: 'https://images.pexels.com/photos/17752158/pexels-photo-17752158.jpeg?auto=compress&cs=tinysrgb&w=400',
    rating: 4.4,
    episodeCount: 6,
    price: 0,
    isFree: true,
    isNew: false,
    isTrending: false,
    readProgress: 100,
    isBookmarked: false,
    previewPages: ['page1.jpg', 'page2.jpg', 'page3.jpg'],
    language: 'English',
    createdAt: '2023-07-01'
  },
  {
    id: '7',
    title: 'The Griot\'s Tale',
    creator: 'Sekou Traore',
    genre: ['Historical', 'Drama'],
    description: 'A master griot travels across the Mali Empire, preserving stories and witnessing the rise and fall of kingdoms. Music, memory, and magic intertwine.',
    coverImage: 'https://images.pexels.com/photos/16784072/pexels-photo-16784072.jpeg?auto=compress&cs=tinysrgb&w=400',
    rating: 4.8,
    episodeCount: 14,
    price: 3.99,
    isFree: false,
    isNew: false,
    isTrending: false,
    readProgress: 0,
    isBookmarked: false,
    previewPages: ['page1.jpg', 'page2.jpg', 'page3.jpg'],
    language: 'English',
    createdAt: '2023-06-12'
  },
  {
    id: '8',
    title: 'Cairo Mysteries',
    creator: 'Yasmin El-Masry',
    genre: ['Mystery', 'Thriller'],
    description: 'A detective in modern Cairo uncovers mysteries that span from ancient pharaohs to contemporary conspiracies. The past and present collide in unexpected ways.',
    coverImage: 'https://images.pexels.com/photos/17191327/pexels-photo-17191327.jpeg?auto=compress&cs=tinysrgb&w=400',
    rating: 4.3,
    episodeCount: 9,
    price: 2.99,
    isFree: false,
    isNew: true,
    isTrending: false,
    readProgress: 15,
    isBookmarked: true,
    previewPages: ['page1.jpg', 'page2.jpg', 'page3.jpg'],
    language: 'English',
    createdAt: '2024-01-20'
  }
];

export const getComicById = (id: string): Comic | undefined => {
  return mockComics.find(comic => comic.id === id);
};

export const getComicsByGenre = (genre: string): Comic[] => {
  return mockComics.filter(comic => comic.genre.includes(genre));
};

export const getTrendingComics = (): Comic[] => {
  return mockComics.filter(comic => comic.isTrending);
};

export const getNewComics = (): Comic[] => {
  return mockComics.filter(comic => comic.isNew);
};

export const getFreeComics = (): Comic[] => {
  return mockComics.filter(comic => comic.isFree);
};