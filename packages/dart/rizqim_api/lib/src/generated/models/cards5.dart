// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'cards5.g.dart';

@JsonSerializable()
class Cards5 {
  const Cards5({
    required this.cardType,
    required this.count,
    required this.amount,
  });

  factory Cards5.fromJson(Map<String, Object?> json) => _$Cards5FromJson(json);

  @JsonKey(name: 'card_type')
  final String cardType;
  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Cards5ToJson(this);
}
