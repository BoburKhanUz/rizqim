// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data97.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data97 _$Data97FromJson(Map<String, dynamic> json) => Data97(
  sets: (json['sets'] as List<dynamic>)
      .map((e) => Sets.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data97ToJson(Data97 instance) => <String, dynamic>{
  'sets': instance.sets,
};
