// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'lines9.dart';
import 'room_fee4.dart';
import 'channels3.dart';

part 'data130.g.dart';

@JsonSerializable()
class Data130 {
  const Data130({
    required this.text,
    required this.lines,
    required this.roomFee,
    required this.channels,
  });

  factory Data130.fromJson(Map<String, Object?> json) =>
      _$Data130FromJson(json);

  final String text;
  final List<Lines9> lines;
  @JsonKey(name: 'room_fee')
  final RoomFee4? roomFee;
  final Channels3 channels;

  Map<String, Object?> toJson() => _$Data130ToJson(this);
}
