// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data88.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data88 _$Data88FromJson(Map<String, dynamic> json) => Data88(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data88ToJson(Data88 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
