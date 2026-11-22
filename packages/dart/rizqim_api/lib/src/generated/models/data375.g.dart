// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data375.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data375 _$Data375FromJson(Map<String, dynamic> json) => Data375(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data375ToJson(Data375 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
