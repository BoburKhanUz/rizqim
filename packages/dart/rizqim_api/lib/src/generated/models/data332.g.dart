// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data332.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data332 _$Data332FromJson(Map<String, dynamic> json) => Data332(
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data332ToJson(Data332 instance) => <String, dynamic>{
  'slots': instance.slots,
};
