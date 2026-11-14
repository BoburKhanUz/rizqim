// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'channel3.dart';

part 'channels3.g.dart';

@JsonSerializable()
class Channels3 {
  const Channels3({required this.channel, this.isHidden, this.pricePercent});

  factory Channels3.fromJson(Map<String, Object?> json) =>
      _$Channels3FromJson(json);

  final Channel3 channel;
  @JsonKey(name: 'is_hidden')
  final bool? isHidden;
  @JsonKey(name: 'price_percent')
  final num? pricePercent;

  Map<String, Object?> toJson() => _$Channels3ToJson(this);
}
