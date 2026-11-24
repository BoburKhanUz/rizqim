// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'combos.dart';
import 'combo_fiscal_mode.dart';

part 'data63.g.dart';

@JsonSerializable()
class Data63 {
  const Data63({required this.combos, required this.fiscalMode});

  factory Data63.fromJson(Map<String, Object?> json) => _$Data63FromJson(json);

  final List<Combos> combos;
  @JsonKey(name: 'fiscal_mode')
  final ComboFiscalMode fiscalMode;

  Map<String, Object?> toJson() => _$Data63ToJson(this);
}
