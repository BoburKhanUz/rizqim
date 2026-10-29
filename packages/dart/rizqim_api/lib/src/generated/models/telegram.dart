// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'telegram.g.dart';

@JsonSerializable()
class Telegram {
  const Telegram({required this.groupLink, required this.groupLinked});

  factory Telegram.fromJson(Map<String, Object?> json) =>
      _$TelegramFromJson(json);

  @JsonKey(name: 'group_link')
  final String? groupLink;
  @JsonKey(name: 'group_linked')
  final bool groupLinked;

  Map<String, Object?> toJson() => _$TelegramToJson(this);
}
