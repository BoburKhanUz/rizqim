// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data119.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data119 _$Data119FromJson(Map<String, dynamic> json) => Data119(
  sets: (json['sets'] as List<dynamic>)
      .map((e) => Sets.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data119ToJson(Data119 instance) => <String, dynamic>{
  'sets': instance.sets,
};
