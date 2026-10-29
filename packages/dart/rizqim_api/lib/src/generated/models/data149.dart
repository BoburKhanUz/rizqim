// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'lines9.dart';
import 'room_fee4.dart';
import 'channels3.dart';

part 'data149.g.dart';

@JsonSerializable()
class Data149 {
  const Data149({
    required this.text,
    required this.lines,
    required this.roomFee,
    required this.channels,
  });

  factory Data149.fromJson(Map<String, Object?> json) =>
      _$Data149FromJson(json);

  final String text;
  final List<Lines9> lines;
  @JsonKey(name: 'room_fee')
  final RoomFee4? roomFee;
  final Channels3 channels;

  Map<String, Object?> toJson() => _$Data149ToJson(this);
}
