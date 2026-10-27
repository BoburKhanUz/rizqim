// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data210.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data210 _$Data210FromJson(Map<String, dynamic> json) => Data210(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data210ToJson(Data210 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
