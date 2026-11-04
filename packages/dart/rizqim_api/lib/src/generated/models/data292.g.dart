// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data292.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data292 _$Data292FromJson(Map<String, dynamic> json) => Data292(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data292ToJson(Data292 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
