// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data368.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data368 _$Data368FromJson(Map<String, dynamic> json) => Data368(
  rating: json['rating'] as String,
  reviewCount: (json['review_count'] as num).toInt(),
);

Map<String, dynamic> _$Data368ToJson(Data368 instance) => <String, dynamic>{
  'rating': instance.rating,
  'review_count': instance.reviewCount,
};
