// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data95.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data95 _$Data95FromJson(Map<String, dynamic> json) => Data95(
  sets: (json['sets'] as List<dynamic>)
      .map((e) => Sets.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data95ToJson(Data95 instance) => <String, dynamic>{
  'sets': instance.sets,
};
