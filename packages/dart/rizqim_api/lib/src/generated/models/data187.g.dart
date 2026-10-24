// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data187.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data187 _$Data187FromJson(Map<String, dynamic> json) => Data187(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data187ToJson(Data187 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
