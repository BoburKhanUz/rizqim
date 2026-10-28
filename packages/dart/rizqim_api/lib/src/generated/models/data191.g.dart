// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data191.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data191 _$Data191FromJson(Map<String, dynamic> json) => Data191(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data191ToJson(Data191 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
