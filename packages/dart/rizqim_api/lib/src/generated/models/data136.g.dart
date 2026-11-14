// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data136.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data136 _$Data136FromJson(Map<String, dynamic> json) => Data136(
  halls: (json['halls'] as List<dynamic>)
      .map((e) => Halls.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data136ToJson(Data136 instance) => <String, dynamic>{
  'halls': instance.halls,
};
