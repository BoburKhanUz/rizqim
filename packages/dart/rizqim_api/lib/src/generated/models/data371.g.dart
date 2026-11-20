// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data371.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data371 _$Data371FromJson(Map<String, dynamic> json) => Data371(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data371ToJson(Data371 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
