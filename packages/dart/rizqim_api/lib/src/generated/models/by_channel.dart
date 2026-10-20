// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'by_channel.g.dart';

@JsonSerializable()
class ByChannel {
  const ByChannel({
    required this.channel,
    required this.orders,
    required this.revenue,
  });

  factory ByChannel.fromJson(Map<String, Object?> json) =>
      _$ByChannelFromJson(json);

  final String channel;
  final int orders;
  final num revenue;

  Map<String, Object?> toJson() => _$ByChannelToJson(this);
}
