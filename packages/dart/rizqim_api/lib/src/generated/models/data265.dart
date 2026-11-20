// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel4.dart';

part 'data265.g.dart';

@JsonSerializable()
class Data265 {
  const Data265({required this.channel, required this.queued});

  factory Data265.fromJson(Map<String, Object?> json) =>
      _$Data265FromJson(json);

  final Channel4 channel;
  final bool queued;

  Map<String, Object?> toJson() => _$Data265ToJson(this);
}
