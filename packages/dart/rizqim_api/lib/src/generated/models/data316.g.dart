// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data316.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data316 _$Data316FromJson(Map<String, dynamic> json) => Data316(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data316ToJson(Data316 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
