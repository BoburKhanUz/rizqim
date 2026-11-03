// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data287.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data287 _$Data287FromJson(Map<String, dynamic> json) => Data287(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data287ToJson(Data287 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
