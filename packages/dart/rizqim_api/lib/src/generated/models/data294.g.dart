// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data294.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data294 _$Data294FromJson(Map<String, dynamic> json) => Data294(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data294ToJson(Data294 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
