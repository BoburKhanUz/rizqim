// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data177.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data177 _$Data177FromJson(Map<String, dynamic> json) => Data177(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data177ToJson(Data177 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
