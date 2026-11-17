// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data369.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data369 _$Data369FromJson(Map<String, dynamic> json) => Data369(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data369ToJson(Data369 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
