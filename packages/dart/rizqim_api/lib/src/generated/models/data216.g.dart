// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data216.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data216 _$Data216FromJson(Map<String, dynamic> json) => Data216(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data216ToJson(Data216 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
