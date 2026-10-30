// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data286.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data286 _$Data286FromJson(Map<String, dynamic> json) => Data286(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data286ToJson(Data286 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
