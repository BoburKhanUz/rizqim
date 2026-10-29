// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data178.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data178 _$Data178FromJson(Map<String, dynamic> json) => Data178(
  text: json['text'] as String,
  lines: (json['lines'] as List<dynamic>)
      .map((e) => Lines19.fromJson(e as Map<String, dynamic>))
      .toList(),
  roomFee: json['room_fee'] == null
      ? null
      : RoomFee4.fromJson(json['room_fee'] as Map<String, dynamic>),
  channels: Channels3.fromJson(json['channels'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data178ToJson(Data178 instance) => <String, dynamic>{
  'text': instance.text,
  'lines': instance.lines,
  'room_fee': instance.roomFee,
  'channels': instance.channels,
};
