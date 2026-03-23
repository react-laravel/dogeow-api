<?php

// 小学英语词汇，供 CET46WordSeeder 使用
$words = [
    // 基础词汇
    'a', 'an', 'the', 'I', 'you', 'he', 'she', 'it', 'we', 'they',
    'my', 'your', 'his', 'her', 'its', 'our', 'their', 'this', 'that', 'these',
    'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has',
    'had', 'do', 'does', 'did', 'will', 'would', 'can', 'could', 'may', 'might',
    'must', 'shall', 'should', 'and', 'or', 'but', 'if', 'because', 'so', 'when',
    // 数字
    'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
    'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen', 'twenty',
    'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety', 'hundred', 'thousand', 'first',
    'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh', 'eighth', 'ninth', 'tenth', 'last',
    // 颜色
    'red', 'blue', 'green', 'yellow', 'black', 'white', 'pink', 'orange', 'purple', 'brown',
    'grey', 'gold', 'silver', 'color', 'dark', 'light', 'bright', 'colorful',
    // 家庭
    'family', 'father', 'mother', 'parent', 'brother', 'sister', 'son', 'daughter', 'grandfather', 'grandmother',
    'uncle', 'aunt', 'cousin', 'baby', 'child', 'children', 'boy', 'girl', 'man', 'woman',
    // 身体
    'body', 'head', 'face', 'eye', 'ear', 'nose', 'mouth', 'tooth', 'teeth', 'hair',
    'hand', 'arm', 'leg', 'foot', 'feet', 'finger', 'toe', 'neck', 'back', 'heart',
    // 动物
    'animal', 'dog', 'cat', 'bird', 'fish', 'horse', 'cow', 'pig', 'sheep', 'chicken',
    'duck', 'rabbit', 'mouse', 'tiger', 'lion', 'elephant', 'monkey', 'bear', 'snake', 'frog',
    'ant', 'bee', 'butterfly', 'spider', 'wolf', 'fox', 'deer', 'panda', 'whale', 'shark',
    // 食物
    'food', 'rice', 'noodle', 'bread', 'cake', 'egg', 'meat', 'fish', 'chicken', 'beef',
    'pork', 'vegetable', 'fruit', 'apple', 'banana', 'orange', 'grape', 'strawberry', 'watermelon', 'peach',
    'tomato', 'potato', 'carrot', 'onion', 'milk', 'water', 'juice', 'tea', 'coffee', 'ice',
    'sugar', 'salt', 'butter', 'cheese', 'soup', 'salad', 'pizza', 'hamburger', 'sandwich', 'candy',
    // 饮料和餐具
    'drink', 'cup', 'glass', 'bottle', 'bowl', 'plate', 'dish', 'spoon', 'fork', 'knife',
    'chopstick', 'breakfast', 'lunch', 'dinner', 'meal', 'hungry', 'thirsty', 'full', 'delicious', 'taste',
    // 学校
    'school', 'classroom', 'teacher', 'student', 'class', 'lesson', 'homework', 'book', 'pen', 'pencil',
    'ruler', 'eraser', 'desk', 'chair', 'blackboard', 'chalk', 'paper', 'notebook', 'bag', 'schoolbag',
    'English', 'Chinese', 'math', 'music', 'art', 'PE', 'science', 'history', 'geography', 'computer',
    // 时间
    'time', 'day', 'week', 'month', 'year', 'today', 'tomorrow', 'yesterday', 'morning', 'afternoon',
    'evening', 'night', 'noon', 'hour', 'minute', 'second', 'clock', 'watch', 'calendar', 'birthday',
    'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'weekend',
    'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December',
    'spring', 'summer', 'autumn', 'fall', 'winter', 'season', 'weather', 'sunny', 'cloudy', 'rainy',
    'windy', 'snowy', 'hot', 'cold', 'warm', 'cool',
    // 地点
    'place', 'home', 'house', 'room', 'bedroom', 'bathroom', 'kitchen', 'living', 'garden', 'door',
    'window', 'wall', 'floor', 'roof', 'stairs', 'city', 'town', 'village', 'street', 'road',
    'park', 'zoo', 'farm', 'shop', 'store', 'supermarket', 'market', 'restaurant', 'hotel', 'hospital',
    'library', 'museum', 'cinema', 'theater', 'bank', 'post', 'office', 'station', 'airport', 'bus',
    // 交通
    'car', 'bus', 'train', 'plane', 'bike', 'bicycle', 'boat', 'ship', 'taxi', 'subway',
    'traffic', 'light', 'drive', 'ride', 'fly', 'walk', 'run', 'stop', 'go', 'turn',
    // 动作
    'come', 'go', 'get', 'give', 'take', 'make', 'put', 'see', 'look', 'watch',
    'hear', 'listen', 'say', 'speak', 'talk', 'tell', 'ask', 'answer', 'read', 'write',
    'draw', 'sing', 'dance', 'play', 'work', 'study', 'learn', 'teach', 'think', 'know',
    'understand', 'remember', 'forget', 'try', 'help', 'want', 'need', 'like', 'love', 'hate',
    'open', 'close', 'turn', 'start', 'begin', 'end', 'finish', 'wait', 'find', 'lose',
    'buy', 'sell', 'pay', 'cost', 'spend', 'save', 'use', 'eat', 'drink', 'sleep',
    'wake', 'stand', 'sit', 'lie', 'jump', 'swim', 'climb', 'throw', 'catch', 'kick',
    'hit', 'pull', 'push', 'carry', 'hold', 'drop', 'pick', 'cut', 'break', 'fix',
    'clean', 'wash', 'brush', 'cook', 'bake', 'grow', 'plant', 'water', 'feed', 'keep',
    // 形容词
    'good', 'bad', 'great', 'nice', 'fine', 'beautiful', 'pretty', 'ugly', 'cute', 'lovely',
    'big', 'small', 'large', 'little', 'tall', 'short', 'long', 'high', 'low', 'wide',
    'new', 'old', 'young', 'fast', 'slow', 'quick', 'early', 'late', 'easy', 'hard',
    'difficult', 'simple', 'happy', 'sad', 'angry', 'afraid', 'tired', 'sick', 'healthy', 'strong',
    'weak', 'busy', 'free', 'full', 'empty', 'rich', 'poor', 'cheap', 'expensive', 'same',
    'different', 'special', 'important', 'interesting', 'boring', 'funny', 'strange', 'wonderful', 'terrible', 'wrong',
    'right', 'true', 'false', 'real', 'ready', 'sure', 'safe', 'dangerous', 'clean', 'dirty',
    // 方位
    'up', 'down', 'in', 'out', 'on', 'off', 'over', 'under', 'above', 'below',
    'front', 'back', 'left', 'right', 'middle', 'center', 'side', 'corner', 'top', 'bottom',
    'inside', 'outside', 'near', 'far', 'here', 'there', 'where', 'everywhere', 'somewhere', 'nowhere',
    // 其他常用词
    'thing', 'stuff', 'way', 'kind', 'type', 'part', 'piece', 'bit', 'lot', 'all',
    'some', 'any', 'no', 'none', 'much', 'many', 'more', 'most', 'few', 'little',
    'enough', 'too', 'very', 'really', 'quite', 'just', 'only', 'also', 'again', 'still',
    'already', 'yet', 'ever', 'never', 'always', 'usually', 'often', 'sometimes', 'seldom', 'hardly',
    'maybe', 'perhaps', 'probably', 'certainly', 'of course', 'yes', 'no', 'not', 'please', 'thank',
    'sorry', 'excuse', 'hello', 'hi', 'bye', 'goodbye', 'welcome', 'OK', 'well', 'wow',
    // 节日和活动
    'holiday', 'vacation', 'festival', 'Christmas', 'Easter', 'Halloween', 'party', 'game', 'sport', 'football',
    'basketball', 'tennis', 'ping-pong', 'swimming', 'running', 'skating', 'skiing', 'fishing', 'camping', 'trip',
    'travel', 'visit', 'picnic', 'gift', 'present', 'card', 'photo', 'picture', 'movie', 'show',
    // 自然
    'nature', 'world', 'earth', 'sky', 'sun', 'moon', 'star', 'cloud', 'rain', 'snow',
    'wind', 'air', 'fire', 'water', 'sea', 'ocean', 'river', 'lake', 'mountain', 'hill',
    'tree', 'flower', 'grass', 'leaf', 'forest', 'field', 'land', 'island', 'beach', 'sand',
    'stone', 'rock', 'wood', 'metal', 'gold', 'silver', 'iron', 'glass', 'plastic', 'paper',
    // 衣物
    'clothes', 'coat', 'jacket', 'shirt', 'T-shirt', 'sweater', 'dress', 'skirt', 'pants', 'jeans',
    'shorts', 'sock', 'shoe', 'boot', 'hat', 'cap', 'scarf', 'glove', 'glasses', 'umbrella',
    'bag', 'pocket', 'button', 'zipper', 'belt', 'tie', 'uniform', 'fashion', 'style', 'wear',
    // 职业
    'job', 'work', 'worker', 'doctor', 'nurse', 'teacher', 'driver', 'farmer', 'cook', 'waiter',
    'police', 'fireman', 'soldier', 'artist', 'singer', 'actor', 'writer', 'scientist', 'engineer', 'pilot',
];

return array_map(fn ($w) => ['content' => $w, 'meaning' => ''], array_unique($words));
