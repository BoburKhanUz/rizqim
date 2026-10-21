// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data86.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data86 _$Data86FromJson(Map<String, dynamic> json) => Data86(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data86ToJson(Data86 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
