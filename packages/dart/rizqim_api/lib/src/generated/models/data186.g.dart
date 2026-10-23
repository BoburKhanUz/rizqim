// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data186.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data186 _$Data186FromJson(Map<String, dynamic> json) => Data186(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data186ToJson(Data186 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
