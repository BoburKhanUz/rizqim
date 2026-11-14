// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data329.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data329 _$Data329FromJson(Map<String, dynamic> json) => Data329(
  slots: (json['slots'] as List<dynamic>)
      .map((e) => Slots10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data329ToJson(Data329 instance) => <String, dynamic>{
  'slots': instance.slots,
};
