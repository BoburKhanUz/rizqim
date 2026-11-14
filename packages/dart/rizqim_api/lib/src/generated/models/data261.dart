// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'lines32.dart';
import 'room_fee4.dart';
import 'channels4.dart';

part 'data261.g.dart';

@JsonSerializable()
class Data261 {
  const Data261({
    required this.text,
    required this.lines,
    required this.roomFee,
    required this.channels,
  });

  factory Data261.fromJson(Map<String, Object?> json) =>
      _$Data261FromJson(json);

  final String text;
  final List<Lines32> lines;
  @JsonKey(name: 'room_fee')
  final RoomFee4? roomFee;
  final Channels4 channels;

  Map<String, Object?> toJson() => _$Data261ToJson(this);
}
