// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel4.dart';

part 'data263.g.dart';

@JsonSerializable()
class Data263 {
  const Data263({required this.channel, required this.queued});

  factory Data263.fromJson(Map<String, Object?> json) =>
      _$Data263FromJson(json);

  final Channel4 channel;
  final bool queued;

  Map<String, Object?> toJson() => _$Data263ToJson(this);
}
