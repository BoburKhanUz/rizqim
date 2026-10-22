// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'cards2.g.dart';

@JsonSerializable()
class Cards2 {
  const Cards2({
    required this.cardType,
    required this.count,
    required this.amount,
  });

  factory Cards2.fromJson(Map<String, Object?> json) => _$Cards2FromJson(json);

  @JsonKey(name: 'card_type')
  final String cardType;
  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Cards2ToJson(this);
}
