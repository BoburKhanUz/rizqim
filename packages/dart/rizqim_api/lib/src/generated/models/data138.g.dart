// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data138.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data138 _$Data138FromJson(Map<String, dynamic> json) => Data138(
  halls: (json['halls'] as List<dynamic>)
      .map((e) => Halls.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data138ToJson(Data138 instance) => <String, dynamic>{
  'halls': instance.halls,
};
