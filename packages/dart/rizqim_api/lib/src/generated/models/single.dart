// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'single.g.dart';

@JsonSerializable()
class Single {
  const Single({
    required this.name,
    required this.mxikCode,
    required this.quantity,
    required this.amount,
  });

  factory Single.fromJson(Map<String, Object?> json) => _$SingleFromJson(json);

  final String name;
  @JsonKey(name: 'mxik_code')
  final String? mxikCode;
  final int quantity;
  final String amount;

  Map<String, Object?> toJson() => _$SingleToJson(this);
}
