// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data112.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data112 _$Data112FromJson(Map<String, dynamic> json) => Data112(
  sets: (json['sets'] as List<dynamic>)
      .map((e) => Sets.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data112ToJson(Data112 instance) => <String, dynamic>{
  'sets': instance.sets,
};
