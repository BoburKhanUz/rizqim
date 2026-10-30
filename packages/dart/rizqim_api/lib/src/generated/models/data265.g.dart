// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data265.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data265 _$Data265FromJson(Map<String, dynamic> json) => Data265(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data265ToJson(Data265 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
