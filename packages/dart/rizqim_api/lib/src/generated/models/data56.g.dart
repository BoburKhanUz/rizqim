// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data56.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data56 _$Data56FromJson(Map<String, dynamic> json) => Data56(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data56ToJson(Data56 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
