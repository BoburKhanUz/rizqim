// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data372.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data372 _$Data372FromJson(Map<String, dynamic> json) => Data372(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data372ToJson(Data372 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
