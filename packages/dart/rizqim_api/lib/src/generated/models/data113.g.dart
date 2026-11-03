// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data113.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data113 _$Data113FromJson(Map<String, dynamic> json) => Data113(
  sets: (json['sets'] as List<dynamic>)
      .map((e) => Sets.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data113ToJson(Data113 instance) => <String, dynamic>{
  'sets': instance.sets,
};
