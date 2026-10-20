// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data57.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data57 _$Data57FromJson(Map<String, dynamic> json) => Data57(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data57ToJson(Data57 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
