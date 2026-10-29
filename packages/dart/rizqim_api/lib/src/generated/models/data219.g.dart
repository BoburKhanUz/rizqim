// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data219.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data219 _$Data219FromJson(Map<String, dynamic> json) => Data219(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data219ToJson(Data219 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
