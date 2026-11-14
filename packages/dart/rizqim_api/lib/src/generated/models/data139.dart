// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'osh2.dart';
import 'portion_weights2.dart';
import 'mxik2.dart';

part 'data139.g.dart';

@JsonSerializable()
class Data139 {
  const Data139({
    required this.reservePercent,
    required this.osh,
    required this.portionWeights,
    required this.mxik,
    required this.contractTemplate,
    required this.contractTemplateIsDefault,
    required this.contractPlaceholders,
    required this.version,
  });

  factory Data139.fromJson(Map<String, Object?> json) =>
      _$Data139FromJson(json);

  @JsonKey(name: 'reserve_percent')
  final String reservePercent;
  final Osh2 osh;
  @JsonKey(name: 'portion_weights')
  final List<PortionWeights2> portionWeights;
  final Mxik2 mxik;
  @JsonKey(name: 'contract_template')
  final String contractTemplate;
  @JsonKey(name: 'contract_template_is_default')
  final bool contractTemplateIsDefault;
  @JsonKey(name: 'contract_placeholders')
  final List<String> contractPlaceholders;
  final int version;

  Map<String, Object?> toJson() => _$Data139ToJson(this);
}
