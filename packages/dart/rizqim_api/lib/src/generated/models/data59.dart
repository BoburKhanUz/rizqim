// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'combos.dart';
import 'combo_fiscal_mode.dart';

part 'data59.g.dart';

@JsonSerializable()
class Data59 {
  const Data59({required this.combos, required this.fiscalMode});

  factory Data59.fromJson(Map<String, Object?> json) => _$Data59FromJson(json);

  final List<Combos> combos;
  @JsonKey(name: 'fiscal_mode')
  final ComboFiscalMode fiscalMode;

  Map<String, Object?> toJson() => _$Data59ToJson(this);
}
