// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel4.dart';

part 'data131.g.dart';

@JsonSerializable()
class Data131 {
  const Data131({required this.channel, required this.queued});

  factory Data131.fromJson(Map<String, Object?> json) =>
      _$Data131FromJson(json);

  final Channel4 channel;
  final bool queued;

  Map<String, Object?> toJson() => _$Data131ToJson(this);
}
