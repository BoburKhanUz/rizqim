// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data377.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data377 _$Data377FromJson(Map<String, dynamic> json) => Data377(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data377ToJson(Data377 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
