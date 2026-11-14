// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data261.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data261 _$Data261FromJson(Map<String, dynamic> json) => Data261(
  text: json['text'] as String,
  lines: (json['lines'] as List<dynamic>)
      .map((e) => Lines32.fromJson(e as Map<String, dynamic>))
      .toList(),
  roomFee: json['room_fee'] == null
      ? null
      : RoomFee4.fromJson(json['room_fee'] as Map<String, dynamic>),
  channels: Channels4.fromJson(json['channels'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data261ToJson(Data261 instance) => <String, dynamic>{
  'text': instance.text,
  'lines': instance.lines,
  'room_fee': instance.roomFee,
  'channels': instance.channels,
};
