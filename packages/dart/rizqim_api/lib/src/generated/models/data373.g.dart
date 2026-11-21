// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data373.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data373 _$Data373FromJson(Map<String, dynamic> json) => Data373(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data373ToJson(Data373 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
