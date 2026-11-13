// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data210.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data210 _$Data210FromJson(Map<String, dynamic> json) => Data210(
  text: json['text'] as String,
  lines: (json['lines'] as List<dynamic>)
      .map((e) => Lines21.fromJson(e as Map<String, dynamic>))
      .toList(),
  roomFee: json['room_fee'] == null
      ? null
      : RoomFee4.fromJson(json['room_fee'] as Map<String, dynamic>),
  channels: Channels3.fromJson(json['channels'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data210ToJson(Data210 instance) => <String, dynamic>{
  'text': instance.text,
  'lines': instance.lines,
  'room_fee': instance.roomFee,
  'channels': instance.channels,
};
