// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'osh.dart';
import 'portion_weights.dart';
import 'mxik.dart';

part 'data138.g.dart';

@JsonSerializable()
class Data138 {
  const Data138({
    required this.reservePercent,
    required this.osh,
    required this.portionWeights,
    required this.mxik,
    required this.contractTemplate,
    required this.contractTemplateIsDefault,
    required this.contractPlaceholders,
    required this.version,
  });

  factory Data138.fromJson(Map<String, Object?> json) =>
      _$Data138FromJson(json);

  @JsonKey(name: 'reserve_percent')
  final String reservePercent;
  final Osh osh;
  @JsonKey(name: 'portion_weights')
  final List<PortionWeights> portionWeights;
  final Mxik mxik;
  @JsonKey(name: 'contract_template')
  final String contractTemplate;
  @JsonKey(name: 'contract_template_is_default')
  final bool contractTemplateIsDefault;
  @JsonKey(name: 'contract_placeholders')
  final List<String> contractPlaceholders;
  final int version;

  Map<String, Object?> toJson() => _$Data138ToJson(this);
}
