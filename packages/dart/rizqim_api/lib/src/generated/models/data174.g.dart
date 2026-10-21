// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data174.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data174 _$Data174FromJson(Map<String, dynamic> json) => Data174(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data174ToJson(Data174 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
