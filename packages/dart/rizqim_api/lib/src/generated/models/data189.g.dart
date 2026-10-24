// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data189.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data189 _$Data189FromJson(Map<String, dynamic> json) => Data189(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data189ToJson(Data189 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
